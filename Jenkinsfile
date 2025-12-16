pipeline {
    agent any
    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
        buildDiscarder(logRotator(numToKeepStr: '10'))
    }
    environment {
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }
    parameters {
        choice(name: 'BRANCH_PARAM', choices: ['staging', 'master'], description: 'Select branch to build manually')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION instead of deploy')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Target Docker tag for rollback (if enabled)')
    }
    triggers {
        // Use GitHub webhook trigger (configure in Jenkins job settings)
        githubPush()
    }
    stages {
        stage('Clean Workspace') {
            steps { 
                cleanWs(deleteDirs: true) 
            }
        }
        stage('Checkout Code') {
            steps {
                script {
                    // First, determine what triggered this build
                    echo "Build Trigger: ${currentBuild.getBuildCauses()}"
                    
                    // Check if this was triggered by SCM change
                    def scmVars = null
                    catchError(buildResult: 'SUCCESS', stageResult: 'UNSTABLE') {
                        scmVars = checkout scm
                    }
                    
                    // Get branch/tag information
                    def branchName = ""
                    def isTag = false
                    def tagName = ""
                    
                    // Try to get branch from Git
                    try {
                        branchName = sh(script: 'git symbolic-ref --short HEAD 2>/dev/null || true', returnStdout: true).trim()
                        if (!branchName) {
                            // Might be in detached HEAD (tag checkout)
                            def gitDescribe = sh(script: 'git describe --tags --exact-match HEAD 2>/dev/null || true', returnStdout: true).trim()
                            if (gitDescribe) {
                                isTag = true
                                tagName = gitDescribe
                                branchName = "master" // Checkout master for tag builds
                            }
                        }
                    } catch (Exception e) {
                        echo "Could not determine branch: ${e.message}"
                    }
                    
                    // If branch still not determined, use parameter
                    if (!branchName || branchName == "") {
                        branchName = params.BRANCH_PARAM ?: "staging"
                    }
                    
                    echo "📌 Detected: Branch=${branchName}, IsTag=${isTag}, Tag=${tagName}"
                    
                    // Clean checkout based on what we're building
                    if (isTag) {
                        echo "🎯 Building from tag: ${tagName}"
                        checkout([$class: 'GitSCM',
                            branches: [[name: tagName]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]],
                            extensions: [
                                [$class: 'CloneOption', depth: 0, noTags: false, reference: '', shallow: false],
                                [$class: 'CleanBeforeCheckout'],
                                [$class: 'LocalBranch', localBranch: "**"]
                            ]
                        ])
                    } else {
                        echo "🌿 Building from branch: ${branchName}"
                        checkout([$class: 'GitSCM',
                            branches: [[name: "*/${branchName}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]],
                            extensions: [
                                [$class: 'CloneOption', depth: 0, noTags: false, reference: '', shallow: false],
                                [$class: 'CleanBeforeCheckout'],
                                [$class: 'LocalBranch', localBranch: "**"]
                            ]
                        ])
                    }
                    
                    env.ACTUAL_BRANCH = branchName
                    env.IS_TAG_BUILD = isTag.toString()
                    env.TAG_NAME = tagName ?: ""
                    
                    // Get commit info for logging
                    env.GIT_COMMIT = sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()
                    env.GIT_COMMIT_MESSAGE = sh(script: 'git log -1 --pretty=%B', returnStdout: true).trim()
                }
            }
        }
        stage('Determine Environment') {
            steps {
                script {
                    def isTagBuild = env.IS_TAG_BUILD.toBoolean()
                    
                    if (isTagBuild) {
                        // Tag builds always go to production
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "release"
                        echo "🚀 TAG BUILD detected: ${env.TAG_NAME} → Production"
                    } else if (env.ACTUAL_BRANCH == "staging" || env.ACTUAL_BRANCH == "origin/staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "commit"
                        echo "🧪 STAGING BUILD from branch: ${env.ACTUAL_BRANCH}"
                    } else if (env.ACTUAL_BRANCH == "master" || env.ACTUAL_BRANCH == "origin/master") {
                        // Master branch push - you can choose to skip or build
                        // Option 1: Skip production build (commented out)
                        // echo "⚠️ Master branch push detected. Production builds only on tags."
                        // echo "👉 If you want production, create and push a tag instead."
                        // env.SKIP_BUILD = "true"
                        // return
                        
                        // Option 2: Build but don't deploy (safer)
                        env.DEPLOY_ENV = "master-test"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "test"
                        echo "🔧 MASTER TEST BUILD (not for production deployment)"
                    } else {
                        echo "⚠️ Unknown branch: ${env.ACTUAL_BRANCH}, defaulting to staging"
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "commit"
                    }
                    
                    echo """
                    ========================================
                    Environment Info
                    ========================================
                    Branch:      ${env.ACTUAL_BRANCH}
                    Is Tag:      ${isTagBuild}
                    Tag Name:    ${env.TAG_NAME ?: 'N/A'}
                    Environment: ${env.DEPLOY_ENV}
                    Image:       ${env.IMAGE_NAME}
                    Tag Type:    ${env.TAG_TYPE}
                    Commit:      ${env.GIT_COMMIT}
                    ========================================
                    """
                }
            }
        }
        stage('Generate Docker Tag') {
            when {
                expression { return env.SKIP_BUILD != "true" }
            }
            steps {
                script {
                    def isTagBuild = env.IS_TAG_BUILD.toBoolean()
                    def commitId = env.GIT_COMMIT
                    def imageTag = ""
                    
                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but no TARGET_VERSION provided.")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                        echo "🔄 ROLLBACK to version: ${imageTag}"
                    } else if (isTagBuild) {
                        // Use the actual tag name
                        imageTag = env.TAG_NAME
                        echo "🎯 Using Git tag for Docker tag: ${imageTag}"
                    } else if (env.TAG_TYPE == "commit") {
                        imageTag = "staging-${commitId}"
                        echo "🧪 Staging Docker tag: ${imageTag}"
                    } else if (env.TAG_TYPE == "test") {
                        imageTag = "test-${commitId}"
                        echo "🔧 Test Docker tag: ${imageTag}"
                    } else {
                        imageTag = "prod-${commitId}"
                        echo "🚀 Production Docker tag: ${imageTag}"
                    }
                    
                    env.IMAGE_TAG = imageTag
                    currentBuild.description = "${env.DEPLOY_ENV.toUpperCase()} - ${env.IMAGE_TAG}"
                    
                    echo """
                    ========================================
                    Docker Tag Info
                    ========================================
                    Final Tag: ${env.IMAGE_TAG}
                    Full Image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}
                    ========================================
                    """
                }
            }
        }
        stage('Docker Login') {
            when {
                expression { return env.SKIP_BUILD != "true" }
            }
            steps {
                script {
                    echo "🔐 Logging into Docker Hub..."
                    withCredentials([usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASSWORD'
                    )]) {
                        sh """
                            echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin
                            echo "✅ Docker login successful"
                        """
                    }
                }
            }
        }
        stage('Docker Build & Push') {
            when { 
                allOf {
                    expression { return !params.ROLLBACK }
                    expression { return env.SKIP_BUILD != "true" }
                }
            }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    
                    echo """
                    ========================================
                    Building Docker Image
                    ========================================
                    Image: ${imageFull}
                    Environment: ${env.DEPLOY_ENV}
                    ========================================
                    """
                    
                    sh """
                        # Build with no cache
                        docker build --pull --no-cache -t ${imageFull} .
                        
                        # Push the image
                        docker push ${imageFull}
                        
                        echo "✅ Docker image pushed successfully"
                    """
                    
                    // Create and archive build information
                    def buildInfo = """
                    Build Information
                    ========================================
                    Build Number: ${env.BUILD_NUMBER}
                    Build URL: ${env.BUILD_URL}
                    Environment: ${env.DEPLOY_ENV}
                    Docker Image: ${imageFull}
                    Git Branch: ${env.ACTUAL_BRANCH}
                    Git Tag: ${env.TAG_NAME ?: 'None'}
                    Git Commit: ${env.GIT_COMMIT}
                    Commit Message: ${env.GIT_COMMIT_MESSAGE}
                    Build Time: ${new Date()}
                    Jenkins User: ${env.BUILD_USER_ID ?: 'SYSTEM'}
                    ========================================
                    """
                    
                    writeFile file: 'build-info.txt', text: buildInfo
                    archiveArtifacts artifacts: 'build-info.txt'
                    
                    // Also print to console
                    echo buildInfo
                }
            }
        }
        stage('Push Rollback Version') {
            when {
                allOf {
                    expression { return params.ROLLBACK }
                    expression { return env.SKIP_BUILD != "true" }
                }
            }
            steps {
                script {
                    echo "🔄 Performing rollback to version: ${env.IMAGE_TAG}"
                    // Add your rollback logic here
                    // For example, update Kubernetes deployment to use the rollback tag
                    echo "✅ Rollback prepared for version: ${env.IMAGE_TAG}"
                }
            }
        }
    }
    
    post {
        success {
            echo """
            ========================================
            ✅ BUILD SUCCESSFUL
            ========================================
            Image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}
            Environment: ${env.DEPLOY_ENV}
            Build: ${env.BUILD_NUMBER}
            ========================================
            """
        }
        failure {
            echo """
            ========================================
            ❌ BUILD FAILED
            ========================================
            Check console output for details
            Build: ${env.BUILD_NUMBER}
            ========================================
            """
        }
        always {
            echo "🧹 Cleaning up Docker..."
            sh 'docker logout || true'
            sh 'docker system prune -f || true'
            echo "✅ Cleanup completed"
        }
    }
}
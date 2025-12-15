pipeline {
    agent any
    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }
    environment {
     //   SCANNER_HOME          = tool('sonar-scanner')
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
     //   SONARQUBE_ENV         = "sonar-server"
     //   NAMESPACE             = "reports"
    }
    parameters {
        choice(name: 'BRANCH_PARAM', choices: ['staging', 'master'], description: 'Select branch to build manually')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION instead of deploy')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Target Docker tag for rollback (if enabled)')
    }
    triggers {
        githubPush()
    }
    stages {
        stage('Clean Workspace') {
            steps { cleanWs() }
        }
        stage('Checkout Code') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                    echo ":small_blue_diamond: Checking out branch: ${branchName}"
                    checkout([$class: 'GitSCM',
                        branches: [[name: "*/${branchName}"]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]]
                    ])
                    env.ACTUAL_BRANCH = branchName
                }
            }
        }
        stage('Determine Environment') {
            steps {
                script {
                    if (env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "prophazedocker/staging-report"
        //                env.KUBERNETES_CREDENTIALS_ID = "reports-staging"
        //                env.DEPLOYMENT_FILE = "staging-report.yaml"
        //               env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"
                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "prophazedocker/i-report"
        //                env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging"
        //                env.DEPLOYMENT_FILE = "prod-reports.yaml"
        //              env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"
                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }
                    echo """
                    Environment Info
                    ----------------------
                    Branch: ${env.ACTUAL_BRANCH}
                    Deploy: ${env.DEPLOY_ENV}
                    Repo:   ${env.IMAGE_NAME}
                    Mode:   ${env.TAG_TYPE}
                    Namespace: ${env.NAMESPACE}
                    Deployment File: ${env.DEPLOYMENT_FILE}
                    """
                }
            }
        }
   //     stage('SonarQube Analysis') {
   //         steps {
   //            withSonarQubeEnv("${SONARQUBE_ENV}") {
    //                sh """
    //                   ${SCANNER_HOME}/bin/sonar-scanner \
    //                   -Dsonar.projectName=${env.ACTUAL_BRANCH}-reports \
     //                   -Dsonar.projectKey=${env.ACTUAL_BRANCH}-reports \
      //                  -Dsonar.sources=. \
       //                 -Dsonar.host.url=$SONAR_HOST_URL \
       //                 -Dsonar.login=$SONAR_AUTH_TOKEN
      //              """
      //          }
       //     }
    //    }
     //   stage('Quality Gate') {
       //     steps {
     //           script {
         //           timeout(time: 3, unit: 'MINUTES') {
           //             def qg = waitForQualityGate abortPipeline: false, credentialsId: 'sonar-token'
             //           if (qg.status != 'OK') {
               //             error "Quality Gate failed: ${qg.status}"
                 //       } else {
                   //         echo "Quality Gate passed: ${qg.status}"
                     //   }
                 //   }
               // }
           // }
       // }
     //   stage('Trivy Filesystem Scan') {
       //     steps {
         //       script {
           //         echo "Running Trivy filesystem scan..."
            //        sh "trivy fs . --severity HIGH,CRITICAL > trivyfs.txt || true"
            //        echo "Filesystem scan completed — saved in trivyfs.txt"
              //  }
           // }
   //     }
        stage('Generate Docker Tag') {
            steps {
                script {
                    def commitId = sh(script: "git rev-parse HEAD | cut -c1-7", returnStdout: true).trim()
                    def imageTag = ""
                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but no TARGET_VERSION provided.")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                    } else if (env.TAG_TYPE == "commit") {
                        imageTag = "staging-${commitId}"
                    } else if (env.TAG_TYPE == "release") {
                        def tagName = sh(
                            script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                            returnStdout: true
                        ).trim()
                        if (!tagName) {
                          error("Tag not found. Stopping build.")
                        }
                        imageTag = tagName
                    }
                    env.IMAGE_TAG = imageTag
                    echo ":rocket: FINAL Docker Tag: ${env.IMAGE_TAG}"
                }
            }
        }
    //    stage('Docker Login') {
    //        steps {
    //            script {
    //                withCredentials([usernamePassword(credentialsId: env.DOCKER_CREDENTIALS_ID,
    //                    usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASSWORD')]) {
    //                    sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
    //                }
    //            }
    //        }
    //    }
    //    stage('Docker Build & Push') {
    //        when { expression { return !params.ROLLBACK } }
    //        steps {
    //            script {
    //                def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
    //                echo "Building Docker image: ${imageFull}"
    //                sh """
    //                    docker build --pull --no-cache -t ${imageFull} .
    //                  docker push ${imageFull}
    //              """
    //                sh "docker logout"
    //            }
    //        }
    //    }
    //    stage('Trivy Image Scan') {
    //        when { expression { return !params.ROLLBACK } }
    //        steps {
    //            script {
    //                echo "Running Trivy image scan..."
    //                sh """
    //                    docker pull ${env.IMAGE_NAME}:${env.IMAGE_TAG} || true
    //                    trivy image ${env.IMAGE_NAME}:${env.IMAGE_TAG} --severity HIGH,CRITICAL > trivyimage.txt || true
    //                """
    //                echo "Image scan completed — results saved in trivyimage.txt"
    //            }
    //        }
    //    }
    //    stage('Rollback Version') {
    //        when { expression { return params.ROLLBACK && params.TARGET_VERSION?.trim() } }
    //        steps {
    //            script {
    //                def rollbackVersion = params.TARGET_VERSION.trim()
    //                echo "Rolling back to version: ${rollbackVersion}"
    //                dir('deployments') {
    //                    withKubeConfig(credentialsId: env.KUBERNETES_CREDENTIALS_ID) {
    //                        sh """
    //                            sed -i 's|image: ${env.IMAGE_NAME}:.*|image: ${env.IMAGE_NAME}:${rollbackVersion}|' ${env.DEPLOYMENT_FILE}
    //                            kubectl apply -f ${env.DEPLOYMENT_FILE} -n ${env.NAMESPACE}
    //                            kubectl rollout status deployment/${env.DEPLOYMENT_NAME} -n ${env.NAMESPACE}
    //                        """
    //                    }
    //                }
    //            }
    //        }
    //    }
    //    stage('Deploy to Kubernetes') {
    //        when { expression { return !params.ROLLBACK } }
     //       steps {
    //            script {
    //                dir('deployments') {
    //                    withKubeConfig(credentialsId: env.KUBERNETES_CREDENTIALS_ID) {
    //                        echo "Deploying ${env.IMAGE_NAME}:${env.IMAGE_TAG} to ${env.DEPLOY_ENV} ..."
    //                        sh """
    //                            sed -i 's|image: ${env.IMAGE_NAME}:.*|image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}|' ${env.DEPLOYMENT_FILE}
    //                            kubectl apply -f ${env.DEPLOYMENT_FILE} -n ${env.NAMESPACE}
    //                            kubectl rollout status deployment/${env.DEPLOYMENT_NAME} -n ${env.NAMESPACE} || {
    //                                echo ":warning: Deployment failed, rolling back..."
    //                                kubectl rollout undo deployment/${env.DEPLOYMENT_NAME} -n ${env.NAMESPACE}
    //                                exit 1
    //                            }
    //                        """
    //                    }
    //                }
    //            }
   //         }
    //    }
    }
    post {
        success {
            script {
                slackSend(
                    channel: '#report-ci-watchtower',
                    color: '#36A64F',
                    tokenCredentialId: 'slack-token',
                    message: ":white_check_mark: *Deployment Successful!*\n\n*Env:* ${env.DEPLOY_ENV}\n*Image:* ${env.IMAGE_NAME}:${env.IMAGE_TAG}\n<${env.BUILD_URL}|View Build>"
                )
                emailext(
                    attachLog: true,
                    subject: "Jenkins Pipeline Success - ${env.JOB_NAME}",
                    body: """
                        <b>Project:</b> ${env.JOB_NAME}<br/>
                        <b>Build Number:</b> ${env.BUILD_NUMBER}<br/>
                        <b>Status:</b> ${currentBuild.result}<br/>
                        <b>Docker Image:</b> ${env.IMAGE_NAME}:${env.IMAGE_TAG}<br/>
                        <b>Environment:</b> ${env.DEPLOY_ENV}<br/>
                        <b>Namespace:</b> ${env.NAMESPACE}<br/>
                        <b>Deployment File:</b> ${env.DEPLOYMENT_FILE}<br/>
                        <b>URL:</b> <a href="${env.BUILD_URL}">${env.BUILD_URL}</a><br/><br/>
                        Trivy & SonarQube reports attached.
                    """,
                    to: 'infra.alerts@prophaze.com',
                    attachmentsPattern: 'trivyfs.txt,trivyimage.txt'
                )
            }
        }
        failure {
            script {
                slackSend(
                    channel: '#report-ci-watchtower',
                    color: '#FF0000',
                    tokenCredentialId: 'slack-token',
                    message: ":x: *Build Failed!*\n\n*Env:* ${env.DEPLOY_ENV}\n<${env.BUILD_URL}|View Logs>"
                )
            }
        }
        always {
            echo 'Pipeline completed.'
            cleanWs()
        }
    }
}